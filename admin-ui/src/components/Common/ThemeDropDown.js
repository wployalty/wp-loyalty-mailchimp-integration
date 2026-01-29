import React from 'react';

const ThemeDropDown = ({options, setActive, setActiveState, activeColor}) => {
    const handleActive = (item) => {
        setActive(false);
        setActiveState(item.value)
    }
    return <div
        className={`flex   flex-col border rounded-lg bg-white w-full text-light border-light_border z-10 absolute top-11.5 left-0 overflow-hidden`}>
        {
            options.map((item, index) => {
                return <p
                    key={index}
                    onClick={() => {
                        handleActive(item);
                    }}
                    className={`flex items-center  px-4 py-2 justify-between ${item.value === activeColor ? "bg-primary_extra_light text-primary" : "bg-white text-dark "} hover:bg-primary_extra_light cursor-pointer hover:bg-opacity-50`}
                >
                    {item.value}
                    {item.value === activeColor && <span className='flex items-center'>
                            <i
                                className=" wlr wlrf-tick color-important font-medium  text-lg 2xl:text-xl leading-0 cursor-pointer "
                            />
                        </span>}
                </p>
            })
        }
    </div>
};

export default ThemeDropDown;