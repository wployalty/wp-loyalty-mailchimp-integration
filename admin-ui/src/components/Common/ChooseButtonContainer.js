import React from 'react';

const ChooseButtonContainer = ({width = "w-1/2", label, activated, click}) => {
    return <div
        className={`flex whitespace-nowrap cursor-pointer ${width}
            transition duration-200 ease-in items-center space-x-2 px-2.5 py-3   h-11
            ${activated ? "border-2  border-blue_primary " : "border border-light_border "}  rounded-md bg-white`
        }
        onClick={click}
    >
        <div className='flex items-center  rounded-full justify-center p-1 h-5 w-5 bg-white'>
            <i className={` wlr wlrf-${activated ? "tick_circle text-blue_primary" : "tick_circle_2 text-light"}  text-center  font-bold text-md 2xl:text-lg`}/>
        </div>
        <p className={`${activated ? "text-dark" : "text-light"} overflow-hidden text-xs 2xl:text-sm font-normal`}>{label}</p>
    </div>
};

export default ChooseButtonContainer;