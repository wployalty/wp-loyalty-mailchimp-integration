import React from 'react';
import {UiLabelContext} from "../../Context";

const ImageContainer = ({description, handleRemoveImage, handleChooseImage, value}) => {
    const labels = React.useContext(UiLabelContext);
    return <div className={`flex flex-col w-full gap-y-2 2xl:gap-y-3`}>

        {description && <p className={`text-light 2xl:text-sm text-xs font-normal  `}
        >{description}
        </p>}
        <div
            className={`flex items-center justify-center w-full h-50 border-2 border-light_border border-dashed rounded-md`}>
            {["", undefined].includes(value) ?
                <div className={`flex flex-col items-center gap-y-3`}>
                    <i className={`wlr wlrf-image-upload text-light text-3xl`}/>
                    <p className={`text-xs 2xl:text-sm text-light font-medium`}>{labels.common.image_description}</p>
                </div> : <img src={value}
                              alt={"logo image"}
                              className={`object-contain h-full w-full p-1 rounded-md `}
                />}
            {/*                    h-19 w-60*/}
        </div>
        <div className={`flex w-full gap-4 items-center gap-x-3`}>
            <div
                onClick={handleRemoveImage}
                className={`flex items-center cursor-pointer justify-center w-full rounded-md py-3 border border-light_border `}>
                <p className={`text-light 2xl:text-sm text-xs`}>{labels.common.restore_default}</p>
            </div>
            <div
                onClick={handleChooseImage}
                className={`flex items-center cursor-pointer justify-center w-full rounded-md py-3 bg-blue_primary `}>
                <p className={`text-white 2xl:text-sm text-xs`}>{labels.common.browse_image}</p>
            </div>
        </div>
    </div>
};

export default ImageContainer;