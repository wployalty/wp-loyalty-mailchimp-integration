import React from 'react';
import {responsive} from "../../helpers/utilities";
import ThemeDropDown from "./ThemeDropDown";

const ColorChooseContainer = ({
                                  options,
                                  width = "w-1/2",
                                  label,
                                  active,
                                  setActive,
                                  activeColor,
                                  setActiveState,
                                  selectedColorName,
                                  backgroundColor
                              }) => {

    return <div className={`flex flex-col gap-y-1 ${width}`}>
        <p className={`text-light text-xs 2xl:text-sm font-semibold tracking-wider`}>{label}</p>

        <div
            className={`border border-light_border h-11 cursor-pointer py-2 px-2 rounded-md bg-white gap-x-1 2xl:gap-x-2 flex w-full items-center justify-between relative`}
            onClick={() => setActive(!active)}
        >
            <div className={`flex items-center gap-x-1 2xl:gap-x-2`}>
                <span className={`h-6 w-6 rounded-full shadow-empty `} style={{
                    backgroundColor: `${backgroundColor}`
                }
                }/>
                <p className={`text-dark font-normal capitalize ${responsive.text.sm}`}>
                    {selectedColorName}
                </p>
            </div>
            <i className={`wlr wlrf-arrow_right text-white 2xl:text-md text-sm cursor-pointer`}/>

            {/* choose dropdown container*/}
            {active &&
                <ThemeDropDown options={options}
                               active={active}
                               setActive={setActive}
                               setActiveState={setActiveState}
                               activeColor={activeColor}
                />}
        </div>
    </div>

};

export default ColorChooseContainer;